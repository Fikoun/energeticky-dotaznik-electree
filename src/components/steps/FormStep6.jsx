import { useFormContext, useWatch } from 'react-hook-form'
import { FileText, Users, Zap, Settings, UserCheck, Phone, Mail, Badge } from 'lucide-react'
import { validateEmail } from '../../utils/validation'
import { getInputClassName } from '../../utils/fieldStyles'
import PhoneInput from '../PhoneInput'
import FileUploadField from '../FileUploadField'

const FormStep6 = ({ formId }) => {
  const { register, control, setValue, formState: { errors }, trigger, watch } = useFormContext()

  // Watch values pro realtime validaci
  const specialistEmail = watch('specialistEmail')

  // Watch for conditional fields
  const hasEnergeticSpecialist = useWatch({
    control,
    name: 'hasEnergeticSpecialist'
  })

  return (
    <div className="space-y-6">
      <div className="text-center mb-8">
        <div className="flex items-center justify-center mb-4">
          <FileText className="h-8 w-8 text-primary-600 mr-3" />
          <h2 className="text-2xl font-bold text-gray-900">
            Provozní a legislativní rámec
          </h2>
        </div>
        <p className="text-gray-600">
          Informace o legislativních požadavcích a administrativních procesech
        </p>
      </div>

      <div className="space-y-6">
        {/* Předpokládá se připojení k distribuční síti */}
        <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
          <label className="form-label">
            <Zap className="inline h-5 w-5 mr-2" />
            Je požádáno o nové připojení k distribuční soustavě - ČEPS?
          </label>
          <div className="flex gap-6 mt-3">
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="yes"
                {...register('gridConnectionPlanned')}
              />
              Ano
            </label>
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="no"
                {...register('gridConnectionPlanned')}
              />
              Ne
            </label>
          </div>
        </div>

        {/* Navýšení příkonu/výkonu */}
        <div className="bg-red-50 p-4 rounded-lg border border-red-200">
          <label className="form-label">
            <Zap className="inline h-5 w-5 mr-2" />
            Má zákazník požádáno o navýšení příkonu/výkonu?
          </label>
          <div className="flex gap-6 mt-3">
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="yes"
                {...register('powerIncreaseRequested')}
              />
              Ano
            </label>
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="no"
                {...register('powerIncreaseRequested')}
              />
              Ne
            </label>
          </div>
          
          {useWatch({ control, name: 'powerIncreaseRequested' }) === 'yes' && (
            <div className="mt-4 space-y-3">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="form-label">Požadovaný příkon:</label>
                  <div className="flex">
                    <input
                      type="number"
                      className="form-input rounded-r-none"
                      placeholder="Příkon"
                      {...register('requestedPowerIncrease')}
                    />
                    <span className="bg-gray-100 border border-l-0 border-gray-300 px-3 py-3 rounded-r-lg text-gray-600 text-sm">
                      MW
                    </span>
                  </div>
                </div>
                <div>
                  <label className="form-label">Požadovaný výkon:</label>
                  <div className="flex">
                    <input
                      type="number"
                      className="form-input rounded-r-none"
                      placeholder="Výkon"
                      {...register('requestedOutputIncrease')}
                    />
                    <span className="bg-gray-100 border border-l-0 border-gray-300 px-3 py-3 rounded-r-lg text-gray-600 text-sm">
                      MW
                    </span>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Nahrání smlouvy o připojení */}
        <div className="bg-cyan-50 p-4 rounded-lg border border-cyan-200">
          <label className="form-label">
            <FileText className="inline h-5 w-5 mr-2" />
            Nahrát smlouvu o připojení a žádost o připojení
          </label>
          <div className="mt-3 space-y-3">
            <FileUploadField
              name="connectionContractFile"
              label="Smlouva o připojení:"
              accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
              multiple={true}
              formId={formId}
              register={register}
              setValue={setValue}
              watch={watch}
              helpText="Podporované formáty: PDF, Word dokumenty, obrázky (JPG, PNG)"
            />
            <FileUploadField
              name="connectionApplicationFile"
              label="Žádost o připojení:"
              accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
              multiple={true}
              formId={formId}
              register={register}
              setValue={setValue}
              watch={watch}
              helpText="Podporované formáty: PDF, Word dokumenty, obrázky (JPG, PNG)"
            />
          </div>
        </div>

        {/* Kdo podá žádost o připojení */}
        <div className="bg-green-50 p-4 rounded-lg border border-green-200">
          <label className="form-label">
            <Settings className="inline h-5 w-5 mr-2" />
            Kdo podá novou žádost o připojení?
          </label>
          <div className="space-y-3 mt-3">
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="customer"
                {...register('connectionApplicationBy')}
              />
              Zákazník sám
            </label>
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="customerbyelectree"
                {...register('connectionApplicationBy')}
              />
              Zákazník sám na základě podkladů od Electree
            </label>
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="electree"
                {...register('connectionApplicationBy')}
              />
              Firma Electree na základě plné moci 
            </label>
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="undecided"
                {...register('connectionApplicationBy')}
              />
              Ještě nerozhodnuto
            </label>
          </div>
        </div>

        {/* Je zákazník ochoten podepsat plnou moc */}
        <div className="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
          <label className="form-label">
            <UserCheck className="inline h-5 w-5 mr-2" />
            Je zákazník ochoten podepsat plnou moc pro Electree?
          </label>
          <div className="flex gap-6 mt-3">
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="yes"
                {...register('willingToSignPowerOfAttorney')}
              />
              Ano
            </label>
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="no"
                {...register('willingToSignPowerOfAttorney')}
              />
              Ne
            </label>
          </div>
        </div>

        {/* Má zákazník energetického specialistu */}
        <div className="bg-purple-50 p-4 rounded-lg border border-purple-200">
          <label className="form-label">
            <Users className="inline h-5 w-5 mr-2" />
            Má zákazník energetického specialistu nebo správce?
          </label>
          <div className="flex gap-6 mt-3">
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="yes"
                {...register('hasEnergeticSpecialist')}
              />
              Ano
            </label>
            <label className="flex items-center">
              <input 
                type="radio" 
                className="form-radio mr-2"
                value="no"
                {...register('hasEnergeticSpecialist')}
              />
              Ne
            </label>
          </div>
          
          {hasEnergeticSpecialist === 'yes' && (
            <div className="mt-4 space-y-3">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label className="form-label text-sm">
                    <UserCheck className="inline h-4 w-4 mr-1" />
                    Jméno specialisty:
                  </label>
                  <input
                    type="text"
                    className="form-input"
                    placeholder="Jméno a příjmení"
                    {...register('specialistName')}
                  />
                </div>
                <div>
                  <label className="form-label text-sm">
                    <Badge className="inline h-4 w-4 mr-1" />
                    Pozice:
                  </label>
                  <select
                    className="form-input"
                    {...register('specialistPosition')}
                  >
                    <option value="">Vyberte pozici</option>
                    <option value="specialist">Specialista</option>
                    <option value="manager">Správce</option>
                    <option value="company">Externí společnost</option>
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label className="form-label text-sm">
                    <Phone className="inline h-4 w-4 mr-1" />
                    Telefon:
                  </label>
                  <PhoneInput
                    register={register}
                    setValue={setValue}
                    name="specialistPhone"
                    errors={errors}
                    required={false}
                    placeholder="123 456 789"
                    trigger={trigger}
                  />
                </div>
                <div>
                  <label className="form-label text-sm">
                    <Mail className="inline h-4 w-4 mr-1" />
                    Email:
                  </label>
                  <input
                    type="email"
                    className={getInputClassName(errors, 'specialistEmail', specialistEmail, false)}
                    placeholder="email@example.com"
                    {...register('specialistEmail', {
                      validate: validateEmail,
                      onChange: () => trigger('specialistEmail')
                    })}
                  />
                  {errors.specialistEmail && (
                    <p className="text-red-500 text-xs mt-1">{errors.specialistEmail.message}</p>
                  )}
                  <p className="text-gray-500 text-xs mt-1">
                    Ověřujeme existenci domény a platnost formátu
                  </p>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Doplňující informace */}
        <div className="bg-gray-50 p-4 rounded-lg">
          <label className="form-label">Doplňující informace k legislativním a provozním požadavkům:</label>
          <textarea
            className="form-input mt-2"
            rows={3}
            placeholder="Další důležité informace týkající se legislativy, povolení, specifických požadavků..."
            {...register('legislativeNotes')}
          />
        </div>
      </div>

      <div className="bg-orange-50 border border-orange-200 rounded-lg p-4">
        <p className="text-orange-800 text-sm">
          <strong>Upozornění:</strong> Správné vyřízení legislativních požadavků a administrativních procesů je klíčové pro úspěšnou realizaci projektu. 
          Plná moc pro Electree může významně urychlit proces povolování a připojení k distribuční síti.
        </p>
      </div>
    </div>
  )
}

export default FormStep6
